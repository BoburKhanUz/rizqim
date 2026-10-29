// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data193.dart';

part 'post_v1_pos_orders_uuid_request_bill_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidRequestBillResponse {
  const PostV1PosOrdersUuidRequestBillResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidRequestBillResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidRequestBillResponseFromJson(json);

  final bool success;
  final String message;
  final Data193 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidRequestBillResponseToJson(this);
}
