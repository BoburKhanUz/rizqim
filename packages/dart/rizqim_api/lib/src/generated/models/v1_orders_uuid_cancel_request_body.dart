// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'reason.dart';

part 'v1_orders_uuid_cancel_request_body.g.dart';

@JsonSerializable()
class V1OrdersUuidCancelRequestBody {
  const V1OrdersUuidCancelRequestBody({this.reason, this.note});

  factory V1OrdersUuidCancelRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OrdersUuidCancelRequestBodyFromJson(json);

  final Reason? reason;
  final String? note;

  Map<String, Object?> toJson() => _$V1OrdersUuidCancelRequestBodyToJson(this);
}
