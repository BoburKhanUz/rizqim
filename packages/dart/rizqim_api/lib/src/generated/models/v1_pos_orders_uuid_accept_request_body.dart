// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_accept_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidAcceptRequestBody {
  const V1PosOrdersUuidAcceptRequestBody({this.version});

  factory V1PosOrdersUuidAcceptRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidAcceptRequestBodyFromJson(json);

  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidAcceptRequestBodyToJson(this);
}
