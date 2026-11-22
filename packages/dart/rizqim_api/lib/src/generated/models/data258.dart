// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr.dart';

part 'data258.g.dart';

@JsonSerializable()
class Data258 {
  const Data258({required this.qr});

  factory Data258.fromJson(Map<String, Object?> json) =>
      _$Data258FromJson(json);

  final Qr qr;

  Map<String, Object?> toJson() => _$Data258ToJson(this);
}
