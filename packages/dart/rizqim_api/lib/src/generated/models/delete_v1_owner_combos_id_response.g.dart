// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_combos_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerCombosIdResponse _$DeleteV1OwnerCombosIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerCombosIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerCombosIdResponseToJson(
  DeleteV1OwnerCombosIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
