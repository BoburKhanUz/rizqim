// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_dining_tables_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerDiningTablesIdResponse
_$DeleteV1OwnerDiningTablesIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerDiningTablesIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data54.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1OwnerDiningTablesIdResponseToJson(
  DeleteV1OwnerDiningTablesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
