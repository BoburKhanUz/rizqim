// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'state2.dart';

part 'v1_pos_tables_id_state_request_body.g.dart';

@JsonSerializable()
class V1PosTablesIdStateRequestBody {
  const V1PosTablesIdStateRequestBody({required this.state});

  factory V1PosTablesIdStateRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosTablesIdStateRequestBodyFromJson(json);

  final State2 state;

  Map<String, Object?> toJson() => _$V1PosTablesIdStateRequestBodyToJson(this);
}
