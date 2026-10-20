// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_categories_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerCategoriesResponse _$GetV1OwnerCategoriesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerCategoriesResponse(
  success: json['success'] as bool,
  data: (json['data'] as List<dynamic>)
      .map((e) => Category.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerCategoriesResponseToJson(
  GetV1OwnerCategoriesResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
