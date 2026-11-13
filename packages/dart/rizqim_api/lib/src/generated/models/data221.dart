// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal12.dart';

part 'data221.g.dart';

@JsonSerializable()
class Data221 {
  const Data221({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data221.fromJson(Map<String, Object?> json) =>
      _$Data221FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal12 fiscal;

  Map<String, Object?> toJson() => _$Data221ToJson(this);
}
