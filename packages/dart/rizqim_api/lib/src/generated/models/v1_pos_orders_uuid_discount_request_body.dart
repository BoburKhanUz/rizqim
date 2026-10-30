// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind5.dart';

part 'v1_pos_orders_uuid_discount_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidDiscountRequestBody {
  const V1PosOrdersUuidDiscountRequestBody({
    required this.version,
    required this.kind,
    required this.value,
    this.reason,
    this.approvalToken,
  });

  factory V1PosOrdersUuidDiscountRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidDiscountRequestBodyFromJson(json);

  final int version;
  final Kind5 kind;
  final num value;
  final String? reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidDiscountRequestBodyToJson(this);
}
