// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'booking_confirmation.g.dart';

@JsonSerializable()
class BookingConfirmation {
  const BookingConfirmation({
    required this.autoSend,
    required this.sms,
    required this.cancelPolicy,
  });

  factory BookingConfirmation.fromJson(Map<String, Object?> json) =>
      _$BookingConfirmationFromJson(json);

  @JsonKey(name: 'auto_send')
  final bool autoSend;
  final bool sms;
  @JsonKey(name: 'cancel_policy')
  final String? cancelPolicy;

  Map<String, Object?> toJson() => _$BookingConfirmationToJson(this);
}
