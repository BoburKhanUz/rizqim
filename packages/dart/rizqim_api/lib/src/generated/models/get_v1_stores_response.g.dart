// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_stores_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1StoresResponse _$GetV1StoresResponseFromJson(Map<String, dynamic> json) =>
    GetV1StoresResponse(
      success: json['success'] as bool,
      data: (json['data'] as List<dynamic>)
          .map((e) => Store.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$GetV1StoresResponseToJson(
  GetV1StoresResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
