// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_production_sheet_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidProductionSheetResponse
_$GetV1PosBanquetsUuidProductionSheetResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBanquetsUuidProductionSheetResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data236.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBanquetsUuidProductionSheetResponseToJson(
  GetV1PosBanquetsUuidProductionSheetResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
