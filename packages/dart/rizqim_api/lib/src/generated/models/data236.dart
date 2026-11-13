// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account5.dart';
import 'entry2.dart';
import 'fiscal16.dart';

part 'data236.g.dart';

@JsonSerializable()
class Data236 {
  const Data236({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data236.fromJson(Map<String, Object?> json) =>
      _$Data236FromJson(json);

  final Account5 account;
  final Entry2 entry;
  final Fiscal16 fiscal;

  Map<String, Object?> toJson() => _$Data236ToJson(this);
}
