// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'staff_pay.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StaffPay _$StaffPayFromJson(Map<String, dynamic> json) => StaffPay(
  clock: json['clock'] as bool,
  session: json['session'] == null
      ? null
      : Session.fromJson(json['session'] as Map<String, dynamic>),
);

Map<String, dynamic> _$StaffPayToJson(StaffPay instance) => <String, dynamic>{
  'clock': instance.clock,
  'session': instance.session,
};
