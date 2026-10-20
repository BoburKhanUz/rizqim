// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_orders_uuid_assign_courier_request_body.g.dart';

@JsonSerializable()
class V1OrdersUuidAssignCourierRequestBody {
  const V1OrdersUuidAssignCourierRequestBody({required this.courierId});

  factory V1OrdersUuidAssignCourierRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OrdersUuidAssignCourierRequestBodyFromJson(json);

  @JsonKey(name: 'courier_id')
  final int courierId;

  Map<String, Object?> toJson() =>
      _$V1OrdersUuidAssignCourierRequestBodyToJson(this);
}
