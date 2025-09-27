
import sys
import json
from sentence_transformers import SentenceTransformer

MODEL_NAME = "sentence-transformers/all-MiniLM-L6-v2"
model = SentenceTransformer(MODEL_NAME)

# Read JSON from stdin
input_json = sys.stdin.read()
data = json.loads(input_json)
texts = data.get("texts", [])

# Generate embeddings in batch
embeddings = model.encode(texts, normalize_embeddings=True).tolist()

# Output JSON
print(json.dumps({"embeddings": embeddings}))
