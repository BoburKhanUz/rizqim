// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr4.dart';

part 'data337.g.dart';

@JsonSerializable()
class Data337 {
  const Data337({required this.qr});

  factory Data337.fromJson(Map<String, Object?> json) =>
      _$Data337FromJson(json);

  final Qr4 qr;

  Map<String, Object?> toJson() => _$Data337ToJson(this);
}
