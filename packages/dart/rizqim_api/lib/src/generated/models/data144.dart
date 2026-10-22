// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal8.dart';

part 'data144.g.dart';

@JsonSerializable()
class Data144 {
  const Data144({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data144.fromJson(Map<String, Object?> json) =>
      _$Data144FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal8 fiscal;

  Map<String, Object?> toJson() => _$Data144ToJson(this);
}
