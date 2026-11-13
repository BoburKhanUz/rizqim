// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'qr4.dart';

part 'data278.g.dart';

@JsonSerializable()
class Data278 {
  const Data278({required this.qr});

  factory Data278.fromJson(Map<String, Object?> json) =>
      _$Data278FromJson(json);

  final Qr4 qr;

  Map<String, Object?> toJson() => _$Data278ToJson(this);
}
