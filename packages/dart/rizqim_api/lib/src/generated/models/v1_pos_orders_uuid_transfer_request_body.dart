// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_transfer_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidTransferRequestBody {
  const V1PosOrdersUuidTransferRequestBody({
    required this.version,
    required this.waiterId,
    this.reason,
    this.approvalToken,
  });

  factory V1PosOrdersUuidTransferRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidTransferRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'waiter_id')
  final int waiterId;
  final String? reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidTransferRequestBodyToJson(this);
}
