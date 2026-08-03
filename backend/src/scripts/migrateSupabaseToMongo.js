require("dotenv").config({ path: ".env.migration" });

const crypto = require("crypto");
const { createClient } = require("@supabase/supabase-js");
const { MongoClient } = require("mongodb");

const {
  SUPABASE_URL,
  SUPABASE_SERVICE_ROLE_KEY,
  SUPABASE_STATE_TABLE = "app_state",
  SUPABASE_STATE_KEY = "adinn-design-task-manager",
  MONGODB_URI = "mongodb://127.0.0.1:27017",
  MONGODB_DATABASE = "adinn_design_task_manager_dev",
} = process.env;

function requireEnvironment() {
  const missing = [];

  if (!SUPABASE_URL) missing.push("SUPABASE_URL");
  if (!SUPABASE_SERVICE_ROLE_KEY) {
    missing.push("SUPABASE_SERVICE_ROLE_KEY");
  }
  if (!MONGODB_URI) missing.push("MONGODB_URI");
  if (!MONGODB_DATABASE) missing.push("MONGODB_DATABASE");

  if (missing.length) {
    throw new Error(
      `Missing migration environment variables: ${missing.join(", ")}`
    );
  }
}

function stableHash(value) {
  return crypto
    .createHash("sha256")
    .update(JSON.stringify(value))
    .digest("hex");
}

function sanitizeMongoValue(value) {
  if (Array.isArray(value)) {
    return value.map(sanitizeMongoValue);
  }

  if (value && typeof value === "object") {
    const clean = {};

    for (const [key, childValue] of Object.entries(value)) {
      const safeKey = key
        .replace(/^\$/g, "_")
        .replace(/\./g, "_");

      clean[safeKey] = sanitizeMongoValue(childValue);
    }

    return clean;
  }

  return value;
}

function asArray(value) {
  if (!value) return [];
  return Array.isArray(value) ? value : [value];
}

function getDocumentIdentity(collectionName, document) {
  const candidates = [
    document.id,
    document._id,
    document.task_id,
    document.taskId,
    document.user_id,
    document.userId,
    document.notification_id,
    document.notificationId,
    document.email,
    document.key,
    document.name,
  ].filter(Boolean);

  const identity = candidates[0]
    ? String(candidates[0])
    : stableHash(document);

  return `${collectionName}:${identity}`;
}

async function fetchAllRows(supabase, tableName) {
  const pageSize = 1000;
  const rows = [];

  for (let start = 0; ; start += pageSize) {
    const end = start + pageSize - 1;

    const { data, error } = await supabase
      .from(tableName)
      .select("*")
      .range(start, end);

    if (error) {
      throw error;
    }

    rows.push(...(data || []));

    if (!data || data.length < pageSize) {
      break;
    }
  }

  return rows;
}

async function relationalTableExists(supabase, tableName) {
  const { error } = await supabase
    .from(tableName)
    .select("*")
    .limit(1);

  return !error;
}

async function readRelationalData(supabase) {
  const tableNames = [
    "users",
    "tasks",
    "task_files",
    "task_comments",
    "task_history",
    "notifications",
    "designer_ratings",
    "app_settings",
    "verticals",
    "task_categories",
  ];

  const result = {};
  let totalRows = 0;

  for (const tableName of tableNames) {
    const exists = await relationalTableExists(
      supabase,
      tableName
    );

    if (!exists) {
      result[tableName] = [];
      continue;
    }

    const rows = await fetchAllRows(supabase, tableName);
    result[tableName] = rows;
    totalRows += rows.length;
  }

  return {
    source: "relational_tables",
    totalRows,
    collections: result,
  };
}

async function readAppState(supabase) {
  const { data, error } = await supabase
    .from(SUPABASE_STATE_TABLE)
    .select("*")
    .eq("key", SUPABASE_STATE_KEY)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Unable to read ${SUPABASE_STATE_TABLE}: ${error.message}`
    );
  }

  if (!data) {
    throw new Error(
      `No app_state row found for key: ${SUPABASE_STATE_KEY}`
    );
  }

  const state = data.data || {};

  const collections = {
    users: asArray(state.users),
    tasks: asArray(state.tasks),

    task_files: asArray(
      state.task_files ||
        state.taskFiles ||
        state.files
    ),

    task_comments: asArray(
      state.task_comments ||
        state.taskComments ||
        state.comments
    ),

    task_history: asArray(
      state.task_history ||
        state.taskHistory ||
        state.history
    ),

    notifications: asArray(state.notifications),

    designer_ratings: asArray(
      state.designer_ratings ||
        state.designerRatings ||
        state.ratings
    ),

    verticals: asArray(state.verticals),

    task_categories: asArray(
      state.task_categories ||
        state.taskCategories ||
        state.categories
    ),

    app_settings: state.settings
      ? [
          {
            id: "app_settings",
            ...state.settings,
          },
        ]
      : [],
  };

  const totalRows = Object.values(collections).reduce(
    (sum, rows) => sum + rows.length,
    0
  );

  return {
    source: "app_state",
    totalRows,
    collections,
  };
}

async function readSupabaseData(supabase) {
  return readAppState(supabase);
}

async function upsertCollection(
  database,
  collectionName,
  documents
) {
  if (!documents.length) {
    return {
      collectionName,
      sourceCount: 0,
      mongoCount: await database
        .collection(collectionName)
        .countDocuments(),
    };
  }

  const collection = database.collection(collectionName);

  const operations = documents.map((rawDocument) => {
    const document = sanitizeMongoValue(rawDocument);

    const migrationKey = getDocumentIdentity(
      collectionName,
      document
    );

    return {
      updateOne: {
        filter: {
          _migrationKey: migrationKey,
        },
        update: {
          $set: {
            ...document,
            _migrationKey: migrationKey,
            _migrationSource: "supabase",
            _migratedAt: new Date(),
          },
        },
        upsert: true,
      },
    };
  });

  const batchSize = 500;

  for (
    let index = 0;
    index < operations.length;
    index += batchSize
  ) {
    await collection.bulkWrite(
      operations.slice(index, index + batchSize),
      {
        ordered: false,
      }
    );
  }



  return {
    collectionName,
    sourceCount: documents.length,
    mongoCount: await collection.countDocuments(),
  };
}

async function createIndexes(database) {
  const safeIndex = async (
    collectionName,
    keys,
    options = {}
  ) => {
    try {
      await database
        .collection(collectionName)
        .createIndex(keys, options);
    } catch (error) {
      console.warn(
        `Index warning for ${collectionName}: ${error.message}`
      );
    }
  };

  await safeIndex(
    "users",
    { email: 1 },
    {
      unique: true,
      sparse: true,
    }
  );

  await safeIndex(
    "tasks",
    { task_id: 1 },
    {
      unique: true,
      sparse: true,
    }
  );

  await safeIndex("tasks", { assigned_by: 1 });
  await safeIndex("tasks", { assigned_to: 1 });
  await safeIndex("tasks", { status: 1 });
  await safeIndex("tasks", { vertical: 1 });
  await safeIndex("tasks", { deadline: 1 });
  await safeIndex("tasks", { created_at: -1 });

  await safeIndex("task_comments", { task_id: 1 });
  await safeIndex("task_history", { task_id: 1 });
  await safeIndex("task_files", { task_id: 1 });

  await safeIndex("notifications", {
    recipient_id: 1,
    is_read: 1,
  });

  await safeIndex("designer_ratings", {
    designer_id: 1,
  });
}

async function main() {
  requireEnvironment();

  const supabase = createClient(
    SUPABASE_URL,
    SUPABASE_SERVICE_ROLE_KEY,
    {
      auth: {
        persistSession: false,
        autoRefreshToken: false,
      },
    }
  );

  const mongoClient = new MongoClient(MONGODB_URI);

  try {
    console.log("Connecting to local MongoDB...");
    await mongoClient.connect();

    const database = mongoClient.db(MONGODB_DATABASE);

    await database.command({ ping: 1 });

    console.log(
      `Connected to MongoDB: ${MONGODB_DATABASE}`
    );

    console.log(
      "Reading Supabase data in read-only mode..."
    );

    const migrationData = await readSupabaseData(supabase);

    console.log(
      `Supabase source detected: ${migrationData.source}`
    );

    const results = [];

    for (const [
      collectionName,
      documents,
    ] of Object.entries(migrationData.collections)) {
      const result = await upsertCollection(
        database,
        collectionName,
        documents
      );

      results.push(result);

      console.log(
        `${collectionName}: ${result.sourceCount} source documents processed`
      );
    }

    await createIndexes(database);

    console.log("\nMigration summary:");

    for (const result of results) {
      console.log(
        `${result.collectionName}: Supabase ${result.sourceCount}, MongoDB ${result.mongoCount}`
      );
    }

    console.log(
      "\nMigration completed successfully."
    );
    console.log(
      "Supabase data was read only and was not modified."
    );
  } finally {
    await mongoClient.close();
  }
}

main().catch((error) => {
  console.error("\nMigration failed:");
  console.error(error);
  process.exitCode = 1;
});