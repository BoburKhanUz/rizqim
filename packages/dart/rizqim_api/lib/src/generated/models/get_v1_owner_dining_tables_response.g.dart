// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_dining_tables_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerDiningTablesResponse _$GetV1OwnerDiningTablesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerDiningTablesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data50.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerDiningTablesResponseToJson(
  GetV1OwnerDiningTablesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
