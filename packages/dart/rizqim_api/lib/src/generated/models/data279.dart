// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr5.dart';

part 'data279.g.dart';

@JsonSerializable()
class Data279 {
  const Data279({required this.qr});

  factory Data279.fromJson(Map<String, Object?> json) =>
      _$Data279FromJson(json);

  final Qr5 qr;

  Map<String, Object?> toJson() => _$Data279ToJson(this);
}
