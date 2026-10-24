// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_superadmin_stores_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1SuperadminStoresResponse _$GetV1SuperadminStoresResponseFromJson(
  Map<String, dynamic> json,
) => GetV1SuperadminStoresResponse(
  success: json['success'] as bool,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data193.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1SuperadminStoresResponseToJson(
  GetV1SuperadminStoresResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
