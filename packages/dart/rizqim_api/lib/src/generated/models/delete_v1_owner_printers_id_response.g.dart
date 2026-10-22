// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_printers_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerPrintersIdResponse _$DeleteV1OwnerPrintersIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerPrintersIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerPrintersIdResponseToJson(
  DeleteV1OwnerPrintersIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
