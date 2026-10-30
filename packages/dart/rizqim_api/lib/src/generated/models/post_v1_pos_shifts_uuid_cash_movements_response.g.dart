// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_shifts_uuid_cash_movements_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosShiftsUuidCashMovementsResponse
_$PostV1PosShiftsUuidCashMovementsResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosShiftsUuidCashMovementsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data241.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosShiftsUuidCashMovementsResponseToJson(
  PostV1PosShiftsUuidCashMovementsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
