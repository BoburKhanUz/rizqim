// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_fiscal_receipts_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosFiscalReceiptsUuidResponse _$GetV1PosFiscalReceiptsUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosFiscalReceiptsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data219.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosFiscalReceiptsUuidResponseToJson(
  GetV1PosFiscalReceiptsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
