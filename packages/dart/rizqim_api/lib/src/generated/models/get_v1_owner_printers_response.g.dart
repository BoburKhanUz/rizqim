// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_printers_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPrintersResponse _$GetV1OwnerPrintersResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPrintersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data138.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerPrintersResponseToJson(
  GetV1OwnerPrintersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
