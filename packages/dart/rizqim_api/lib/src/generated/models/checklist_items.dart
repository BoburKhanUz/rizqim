// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'checklist_items.g.dart';

@JsonSerializable()
class ChecklistItems {
  const ChecklistItems({
    required this.key,
    required this.label,
    required this.hint,
  });

  factory ChecklistItems.fromJson(Map<String, Object?> json) =>
      _$ChecklistItemsFromJson(json);

  final String key;
  final String label;
  final String hint;

  Map<String, Object?> toJson() => _$ChecklistItemsToJson(this);
}
