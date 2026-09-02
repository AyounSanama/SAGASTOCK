#!/bin/bash

file_path="analyzer/lib/src/summary2/element_builder.dart"
original_string_1="void buildElements({"
modified_string_1="void buildElements2({"
original_string_2="class ElementBuilder {"
modified_string_2="class ElementBuilder2 {"

delay=10

while true
do
  current_time=$(date +%H%M)
  if [[ 10#$current_time -ge 2330 ]]; then
    echo "Stopping script as it is past 23:30."
    # Change it back one last time to leave the file in its original state.
    sed -i '' "s/$modified_string_1/$original_string_1/" "$file_path"
    sed -i '' "s/$modified_string_2/$original_string_2/" "$file_path"
    echo "Reverted to original state before stopping."
    break
  fi

  # Change the strings
  sed -i '' "s/$original_string_1/$modified_string_1/" "$file_path"
  echo "Changed to: $modified_string_1"
  sleep "$delay"
  sed -i '' "s/$original_string_2/$modified_string_2/" "$file_path"
  echo "Changed to: $modified_string_2"
  sleep "$delay"

  # Change them back
  sed -i '' "s/$modified_string_1/$original_string_1/" "$file_path"
  echo "Changed back to: $original_string_1"
  sleep "$delay"
  sed -i '' "s/$modified_string_2/$original_string_2/" "$file_path"
  echo "Changed back to: $original_string_2"
  sleep "$delay"
done
