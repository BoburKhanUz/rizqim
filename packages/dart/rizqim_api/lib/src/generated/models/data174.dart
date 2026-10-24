// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device17.dart';
import 'opened_by7.dart';
import 'closed_by7.dart';
import 'summary4.dart';

part 'data174.g.dart';

@JsonSerializable()
class Data174 {
  const Data174({
    required this.uuid,
    required this.status,
    required this.branchId,
    required this.device,
    required this.businessDate,
    required this.openedAt,
    required this.openedBy,
    required this.openingCash,
    required this.closedAt,
    required this.closedBy,
    required this.expectedCash,
    required this.countedCash,
    required this.countedBreakdown,
    required this.difference,
    required this.zNumber,
    required this.forceClosed,
    required this.closeNote,
    required this.summary,
  });

  factory Data174.fromJson(Map<String, Object?> json) =>
      _$Data174FromJson(json);

  final String uuid;
  final String status;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final Device17? device;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'opened_at')
  final String openedAt;
  @JsonKey(name: 'opened_by')
  final OpenedBy7? openedBy;
  @JsonKey(name: 'opening_cash')
  final String openingCash;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  @JsonKey(name: 'closed_by')
  final ClosedBy7? closedBy;
  @JsonKey(name: 'expected_cash')
  final String? expectedCash;
  @JsonKey(name: 'counted_cash')
  final String? countedCash;
  @JsonKey(name: 'counted_breakdown')
  final Map<String, int>? countedBreakdown;
  final String? difference;
  @JsonKey(name: 'z_number')
  final int? zNumber;
  @JsonKey(name: 'force_closed')
  final bool forceClosed;
  @JsonKey(name: 'close_note')
  final String? closeNote;
  final Summary4? summary;

  Map<String, Object?> toJson() => _$Data174ToJson(this);
}
