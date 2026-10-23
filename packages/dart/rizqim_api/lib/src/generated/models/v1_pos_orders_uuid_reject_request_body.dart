// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'cancel_reason.dart';

part 'v1_pos_orders_uuid_reject_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidRejectRequestBody {
  const V1PosOrdersUuidRejectRequestBody({
    required this.reason,
    this.version,
    this.cancelReason,
    this.approvalToken,
  });

  factory V1PosOrdersUuidRejectRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidRejectRequestBodyFromJson(json);

  final int? version;
  final String reason;
  @JsonKey(name: 'cancel_reason')
  final CancelReason? cancelReason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidRejectRequestBodyToJson(this);
}
