// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'booking_confirmation4.g.dart';

@JsonSerializable()
class BookingConfirmation4 {
  const BookingConfirmation4({
    required this.autoSend,
    required this.sms,
    required this.cancelPolicy,
  });

  factory BookingConfirmation4.fromJson(Map<String, Object?> json) =>
      _$BookingConfirmation4FromJson(json);

  @JsonKey(name: 'auto_send')
  final bool autoSend;
  final bool sms;
  @JsonKey(name: 'cancel_policy')
  final String? cancelPolicy;

  Map<String, Object?> toJson() => _$BookingConfirmation4ToJson(this);
}
