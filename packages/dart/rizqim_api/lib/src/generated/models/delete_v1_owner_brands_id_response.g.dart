// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_brands_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerBrandsIdResponse _$DeleteV1OwnerBrandsIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerBrandsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerBrandsIdResponseToJson(
  DeleteV1OwnerBrandsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
