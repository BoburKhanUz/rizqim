// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr2.dart';

part 'payment5.g.dart';

@JsonSerializable()
class Payment5 {
  const Payment5({
    required this.method,
    required this.tendered,
    required this.change,
    required this.qr,
  });

  factory Payment5.fromJson(Map<String, Object?> json) =>
      _$Payment5FromJson(json);

  final String? method;
  final String? tendered;
  final String? change;
  final Qr2? qr;

  Map<String, Object?> toJson() => _$Payment5ToJson(this);
}
