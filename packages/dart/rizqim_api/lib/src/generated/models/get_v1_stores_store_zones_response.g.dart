// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_stores_store_zones_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1StoresStoreZonesResponse _$GetV1StoresStoreZonesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1StoresStoreZonesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data192.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1StoresStoreZonesResponseToJson(
  GetV1StoresStoreZonesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
