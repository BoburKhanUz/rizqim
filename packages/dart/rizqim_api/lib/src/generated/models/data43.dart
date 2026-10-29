// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'card_mode.dart';
import 'checklist.dart';

part 'data43.g.dart';

@JsonSerializable()
class Data43 {
  const Data43({
    required this.branchId,
    required this.cardMode,
    required this.cardModeLabel,
    required this.checklist,
    required this.checklistComplete,
    required this.differenceThreshold,
    required this.askBankTotal,
    required this.updatedAt,
  });

  factory Data43.fromJson(Map<String, Object?> json) => _$Data43FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'card_mode')
  final CardMode? cardMode;
  @JsonKey(name: 'card_mode_label')
  final String? cardModeLabel;
  final List<Checklist> checklist;
  @JsonKey(name: 'checklist_complete')
  final bool checklistComplete;
  @JsonKey(name: 'difference_threshold')
  final String differenceThreshold;
  @JsonKey(name: 'ask_bank_total')
  final bool askBankTotal;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data43ToJson(this);
}
