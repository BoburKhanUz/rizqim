// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bank_terminal3.dart';

part 'v1_pos_shifts_uuid_close_request_body.g.dart';

@JsonSerializable()
class V1PosShiftsUuidCloseRequestBody {
  const V1PosShiftsUuidCloseRequestBody({
    required this.countedBreakdown,
    this.force,
    this.reason,
    this.approvalToken,
    this.bankTerminal,
  });

  factory V1PosShiftsUuidCloseRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosShiftsUuidCloseRequestBodyFromJson(json);

  @JsonKey(name: 'counted_breakdown')
  final List<int> countedBreakdown;
  final bool? force;
  final String? reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  /// P6-16: the bank terminal's day total from its settlement slip (optional; compared with the shift's card money)
  @JsonKey(name: 'bank_terminal')
  final BankTerminal3? bankTerminal;

  Map<String, Object?> toJson() =>
      _$V1PosShiftsUuidCloseRequestBodyToJson(this);
}
