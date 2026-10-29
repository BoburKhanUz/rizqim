// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fiscal.dart';
import 'card_modes.dart';
import 'checklist_items.dart';
import 'branches2.dart';

part 'data83.g.dart';

@JsonSerializable()
class Data83 {
  const Data83({
    required this.fiscal,
    required this.cardModes,
    required this.checklistItems,
    required this.branches,
  });

  factory Data83.fromJson(Map<String, Object?> json) => _$Data83FromJson(json);

  final Fiscal fiscal;
  @JsonKey(name: 'card_modes')
  final List<CardModes> cardModes;
  @JsonKey(name: 'checklist_items')
  final List<ChecklistItems> checklistItems;
  final List<Branches2> branches;

  Map<String, Object?> toJson() => _$Data83ToJson(this);
}
