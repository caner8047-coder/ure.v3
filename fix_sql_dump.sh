#!/bin/bash

# Configuration
INPUT_FILE="zemureti_dbZem_fixed.sql"
OUTPUT_FILE="zemureti_dbZem_utf8.sql"

echo "Reparing SQL Dump: $INPUT_FILE -> $OUTPUT_FILE"

# 1. Clean up invalid UTF-8 sequences (strip them)
iconv -f UTF-8 -t UTF-8 -c "$INPUT_FILE" > "$OUTPUT_FILE"

if [ $? -eq 0 ]; then
    echo "Encoding conversion successful: $OUTPUT_FILE"
else
    echo "Encoding conversion failed!"
    exit 1
fi

# 2. Add some headers to make it safer for MySQL import
# (Optional) We can use sed to fix obvious issues if any were found
# For now, iconv is the primary fix needed for Turkish characters.

echo "Done. You can now import $OUTPUT_FILE into your database."
echo "Suggested command: mysql -u sail -p laravel < $OUTPUT_FILE"
