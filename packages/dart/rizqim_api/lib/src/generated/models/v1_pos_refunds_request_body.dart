// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_refunds_request_body.g.dart';

@JsonSerializable()
class V1PosRefundsRequestBody {
  const V1PosRefundsRequestBody({
    required this.paymentUuid,
    required this.amount,
    required this.reason,
    this.approvalToken,
    this.shiftUuid,
  });

  factory V1PosRefundsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosRefundsRequestBodyFromJson(json);

  @JsonKey(name: 'payment_uuid')
  final String paymentUuid;
  final num amount;
  final String reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;

  Map<String, Object?> toJson() => _$V1PosRefundsRequestBodyToJson(this);
}
