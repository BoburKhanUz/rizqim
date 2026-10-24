// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_confirmation4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingConfirmation4 _$BookingConfirmation4FromJson(
  Map<String, dynamic> json,
) => BookingConfirmation4(
  autoSend: json['auto_send'] as bool,
  sms: json['sms'] as bool,
  cancelPolicy: json['cancel_policy'] as String?,
);

Map<String, dynamic> _$BookingConfirmation4ToJson(
  BookingConfirmation4 instance,
) => <String, dynamic>{
  'auto_send': instance.autoSend,
  'sms': instance.sms,
  'cancel_policy': instance.cancelPolicy,
};
