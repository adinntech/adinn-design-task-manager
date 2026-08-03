require("dotenv").config();
const { MongoClient } = require("mongodb");

async function main() {
  const client = new MongoClient(process.env.MONGODB_URI);

  try {
    await client.connect();

    const db = client.db(process.env.MONGODB_DATABASE);

    const collections = [
      "users",
      "tasks",
      "task_files",
      "task_comments",
      "task_history",
      "notifications",
      "designer_ratings",
      "app_settings",
      "verticals",
      "task_categories"
    ];

    for (const collectionName of collections) {
      const collection = db.collection(collectionName);
      const indexes = await collection.indexes().catch(() => []);

      for (const index of indexes) {
        if (
          index.name !== "_id_" &&
          index.key &&
          Object.prototype.hasOwnProperty.call(index.key, "_migrationKey")
        ) {
          await collection.dropIndex(index.name);
          console.log(`Dropped ${collectionName}.${index.name}`);
        }
      }
    }

    console.log("Migration-only indexes removed successfully.");
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
