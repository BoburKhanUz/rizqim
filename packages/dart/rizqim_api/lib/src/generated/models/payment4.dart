// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr.dart';

part 'payment4.g.dart';

@JsonSerializable()
class Payment4 {
  const Payment4({this.method, this.tendered, this.change, this.qr});

  factory Payment4.fromJson(Map<String, Object?> json) =>
      _$Payment4FromJson(json);

  final String? method;
  final String? tendered;
  final String? change;
  final Qr? qr;

  Map<String, Object?> toJson() => _$Payment4ToJson(this);
}
