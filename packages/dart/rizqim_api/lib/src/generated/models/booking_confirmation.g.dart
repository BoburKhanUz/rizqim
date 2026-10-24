// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_confirmation.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingConfirmation _$BookingConfirmationFromJson(Map<String, dynamic> json) =>
    BookingConfirmation(
      autoSend: json['auto_send'] as bool,
      sms: json['sms'] as bool,
      cancelPolicy: json['cancel_policy'] as String?,
    );

Map<String, dynamic> _$BookingConfirmationToJson(
  BookingConfirmation instance,
) => <String, dynamic>{
  'auto_send': instance.autoSend,
  'sms': instance.sms,
  'cancel_policy': instance.cancelPolicy,
};
