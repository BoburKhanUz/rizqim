// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'session.dart';

part 'staff_pay.g.dart';

@JsonSerializable()
class StaffPay {
  const StaffPay({required this.clock, required this.session});

  factory StaffPay.fromJson(Map<String, Object?> json) =>
      _$StaffPayFromJson(json);

  final bool clock;
  final Session? session;

  Map<String, Object?> toJson() => _$StaffPayToJson(this);
}
