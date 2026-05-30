<?php

return [
    'chunk_size_words' => (int) env('RAG_CHUNK_SIZE_WORDS', 180),
    'chunk_overlap_words' => (int) env('RAG_CHUNK_OVERLAP_WORDS', 30),
];
