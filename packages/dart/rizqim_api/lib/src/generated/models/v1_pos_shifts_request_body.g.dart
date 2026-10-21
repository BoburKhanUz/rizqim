// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_shifts_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosShiftsRequestBody _$V1PosShiftsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosShiftsRequestBody(
  openingCash: json['opening_cash'] as num,
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1PosShiftsRequestBodyToJson(
  V1PosShiftsRequestBody instance,
) => <String, dynamic>{
  'opening_cash': instance.openingCash,
  'note': instance.note,
};
