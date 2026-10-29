// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'warnings5.dart';
import 'lines11.dart';
import 'open_shifts4.dart';

part 'data152.g.dart';

@JsonSerializable()
class Data152 {
  const Data152({
    required this.uuid,
    required this.branchId,
    required this.period,
    required this.periodLabel,
    required this.periodFrom,
    required this.periodTo,
    required this.status,
    required this.statusLabel,
    required this.grossTotal,
    required this.payableTotal,
    required this.paidTotal,
    required this.linesCount,
    required this.warnings,
    required this.computedAt,
    required this.closedAt,
    required this.version,
    required this.createdAt,
    required this.lines,
    required this.openShifts,
  });

  factory Data152.fromJson(Map<String, Object?> json) =>
      _$Data152FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String period;
  @JsonKey(name: 'period_label')
  final String periodLabel;
  @JsonKey(name: 'period_from')
  final String periodFrom;
  @JsonKey(name: 'period_to')
  final String periodTo;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  @JsonKey(name: 'gross_total')
  final String grossTotal;
  @JsonKey(name: 'payable_total')
  final String payableTotal;
  @JsonKey(name: 'paid_total')
  final String paidTotal;
  @JsonKey(name: 'lines_count')
  final int linesCount;
  final List<Warnings5> warnings;
  @JsonKey(name: 'computed_at')
  final String? computedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  final int version;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  final List<Lines11> lines;
  @JsonKey(name: 'open_shifts')
  final List<OpenShifts4> openShifts;

  Map<String, Object?> toJson() => _$Data152ToJson(this);
}
