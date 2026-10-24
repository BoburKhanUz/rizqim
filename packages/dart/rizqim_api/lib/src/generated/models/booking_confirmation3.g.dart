// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_confirmation3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingConfirmation3 _$BookingConfirmation3FromJson(
  Map<String, dynamic> json,
) => BookingConfirmation3(
  autoSend: json['auto_send'] as bool?,
  sms: json['sms'] as bool?,
  cancelPolicy: json['cancel_policy'] as String?,
);

Map<String, dynamic> _$BookingConfirmation3ToJson(
  BookingConfirmation3 instance,
) => <String, dynamic>{
  'auto_send': instance.autoSend,
  'sms': instance.sms,
  'cancel_policy': instance.cancelPolicy,
};
