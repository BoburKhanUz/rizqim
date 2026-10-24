// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_confirmation2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingConfirmation2 _$BookingConfirmation2FromJson(
  Map<String, dynamic> json,
) => BookingConfirmation2(
  autoSend: json['auto_send'] as bool,
  sms: json['sms'] as bool,
  cancelPolicy: json['cancel_policy'] as String?,
);

Map<String, dynamic> _$BookingConfirmation2ToJson(
  BookingConfirmation2 instance,
) => <String, dynamic>{
  'auto_send': instance.autoSend,
  'sms': instance.sms,
  'cancel_policy': instance.cancelPolicy,
};
