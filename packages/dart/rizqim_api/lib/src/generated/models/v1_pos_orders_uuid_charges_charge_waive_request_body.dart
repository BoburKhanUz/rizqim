// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_charges_charge_waive_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidChargesChargeWaiveRequestBody {
  const V1PosOrdersUuidChargesChargeWaiveRequestBody({
    required this.version,
    required this.reason,
    this.approvalToken,
  });

  factory V1PosOrdersUuidChargesChargeWaiveRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidChargesChargeWaiveRequestBodyFromJson(json);

  final int version;
  final String reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidChargesChargeWaiveRequestBodyToJson(this);
}
