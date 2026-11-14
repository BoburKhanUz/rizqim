// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_osh_calculator_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsOshCalculatorResponse
_$GetV1PosBanquetsOshCalculatorResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsOshCalculatorResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data222.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsOshCalculatorResponseToJson(
  GetV1PosBanquetsOshCalculatorResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
