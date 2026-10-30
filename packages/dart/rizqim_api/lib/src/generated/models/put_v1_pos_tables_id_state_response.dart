// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data268.dart';

part 'put_v1_pos_tables_id_state_response.g.dart';

@JsonSerializable()
class PutV1PosTablesIdStateResponse {
  const PutV1PosTablesIdStateResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1PosTablesIdStateResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1PosTablesIdStateResponseFromJson(json);

  final bool success;
  final String message;
  final Data268 data;

  Map<String, Object?> toJson() => _$PutV1PosTablesIdStateResponseToJson(this);
}
