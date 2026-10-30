// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_pos_tables_id_state_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1PosTablesIdStateResponse _$PutV1PosTablesIdStateResponseFromJson(
  Map<String, dynamic> json,
) => PutV1PosTablesIdStateResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data268.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1PosTablesIdStateResponseToJson(
  PutV1PosTablesIdStateResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
