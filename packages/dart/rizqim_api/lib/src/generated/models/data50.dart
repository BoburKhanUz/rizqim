// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'card_mode.dart';
import 'checklist.dart';

part 'data50.g.dart';

@JsonSerializable()
class Data50 {
  const Data50({
    required this.branchId,
    required this.cardMode,
    required this.cardModeLabel,
    required this.checklist,
    required this.checklistComplete,
    required this.differenceThreshold,
    required this.askBankTotal,
    required this.sharedTerminal,
    required this.updatedAt,
  });

  factory Data50.fromJson(Map<String, Object?> json) => _$Data50FromJson(json);

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
  @JsonKey(name: 'shared_terminal')
  final bool sharedTerminal;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data50ToJson(this);
}
