// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'closed_by3.dart';
import 'lines7.dart';

part 'data118.g.dart';

@JsonSerializable()
class Data118 {
  const Data118({
    required this.uuid,
    required this.branchId,
    required this.businessDate,
    required this.shiftUuid,
    required this.rule,
    required this.roles,
    required this.roleWeights,
    required this.poolAmount,
    required this.tipsCount,
    required this.distributed,
    required this.status,
    required this.computedAt,
    required this.closedAt,
    required this.closedBy,
    required this.manualParticipants,
    required this.lines,
  });

  factory Data118.fromJson(Map<String, Object?> json) =>
      _$Data118FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  final String rule;
  final List<String> roles;
  @JsonKey(name: 'role_weights')
  final Map<String, String> roleWeights;
  @JsonKey(name: 'pool_amount')
  final String poolAmount;
  @JsonKey(name: 'tips_count')
  final int tipsCount;
  final String distributed;
  final String status;
  @JsonKey(name: 'computed_at')
  final String? computedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  @JsonKey(name: 'closed_by')
  final ClosedBy3? closedBy;
  @JsonKey(name: 'manual_participants')
  final bool manualParticipants;
  final List<Lines7> lines;

  Map<String, Object?> toJson() => _$Data118ToJson(this);
}
