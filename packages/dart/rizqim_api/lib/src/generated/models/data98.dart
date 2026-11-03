// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fiscal2.dart';
import 'card_modes.dart';
import 'checklist_items.dart';
import 'branches2.dart';

part 'data98.g.dart';

@JsonSerializable()
class Data98 {
  const Data98({
    required this.fiscal,
    required this.cardModes,
    required this.checklistItems,
    required this.branches,
  });

  factory Data98.fromJson(Map<String, Object?> json) => _$Data98FromJson(json);

  final Fiscal2 fiscal;
  @JsonKey(name: 'card_modes')
  final List<CardModes> cardModes;
  @JsonKey(name: 'checklist_items')
  final List<ChecklistItems> checklistItems;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data98ToJson(this);
}
