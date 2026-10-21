// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_move_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidMoveRequestBody {
  const V1PosOrdersUuidMoveRequestBody({
    required this.version,
    required this.tableId,
  });

  factory V1PosOrdersUuidMoveRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersUuidMoveRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'table_id')
  final int tableId;

  Map<String, Object?> toJson() => _$V1PosOrdersUuidMoveRequestBodyToJson(this);
}
