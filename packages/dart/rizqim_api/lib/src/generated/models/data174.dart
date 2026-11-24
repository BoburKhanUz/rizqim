// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'vouchers.dart';
import 'meta.dart';

part 'data174.g.dart';

@JsonSerializable()
class Data174 {
  const Data174({required this.vouchers, required this.meta});

  factory Data174.fromJson(Map<String, Object?> json) =>
      _$Data174FromJson(json);

  final List<Vouchers> vouchers;
  final Meta meta;

  Map<String, Object?> toJson() => _$Data174ToJson(this);
}
