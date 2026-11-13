// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr6.dart';

part 'data280.g.dart';

@JsonSerializable()
class Data280 {
  const Data280({required this.qr});

  factory Data280.fromJson(Map<String, Object?> json) =>
      _$Data280FromJson(json);

  final Qr6 qr;

  Map<String, Object?> toJson() => _$Data280ToJson(this);
}
