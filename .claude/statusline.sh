#!/bin/bash
input=$(cat)

CONTEXT_WINDOW_USED_PERCENTAGE=$(echo "$input" | jq -r '.context_window.used_percentage // 0 | floor')
COST_TOTAL_COST_USD=$(echo "$input" | jq -r '.cost.total_cost_usd // 0 | . * 100 | floor / 100 | tostring | if . | contains(".") then . else . + ".00" end | if (. | split(".")[1] | length) == 1 then . + "0" else . end')
MODEL_DISPLAY_NAME=$(echo "$input" | jq -r '.model.display_name')
CWD=$(echo "$input" | jq -r '.cwd')

echo "Context used: ${CONTEXT_WINDOW_USED_PERCENTAGE}% | Cost: \$${COST_TOTAL_COST_USD} | Model: $MODEL_DISPLAY_NAME | CWD: $CWD"