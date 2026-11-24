// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'halls.dart';

part 'data139.g.dart';

@JsonSerializable()
class Data139 {
  const Data139({required this.halls});

  factory Data139.fromJson(Map<String, Object?> json) =>
      _$Data139FromJson(json);

  final List<Halls> halls;

  Map<String, Object?> toJson() => _$Data139ToJson(this);
}
