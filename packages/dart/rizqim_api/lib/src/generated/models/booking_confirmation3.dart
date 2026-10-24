// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'booking_confirmation3.g.dart';

@JsonSerializable()
class BookingConfirmation3 {
  const BookingConfirmation3({this.autoSend, this.sms, this.cancelPolicy});

  factory BookingConfirmation3.fromJson(Map<String, Object?> json) =>
      _$BookingConfirmation3FromJson(json);

  @JsonKey(name: 'auto_send')
  final bool? autoSend;
  final bool? sms;
  @JsonKey(name: 'cancel_policy')
  final String? cancelPolicy;

  Map<String, Object?> toJson() => _$BookingConfirmation3ToJson(this);
}
