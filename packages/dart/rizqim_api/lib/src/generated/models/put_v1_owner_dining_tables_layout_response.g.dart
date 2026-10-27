// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_dining_tables_layout_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerDiningTablesLayoutResponse
_$PutV1OwnerDiningTablesLayoutResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerDiningTablesLayoutResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: (json['data'] as List<dynamic>)
          .map((e) => Data73.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$PutV1OwnerDiningTablesLayoutResponseToJson(
  PutV1OwnerDiningTablesLayoutResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
