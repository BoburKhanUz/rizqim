// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account5.dart';
import 'entry4.dart';
import 'fiscal32.dart';

part 'data291.g.dart';

@JsonSerializable()
class Data291 {
  const Data291({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data291.fromJson(Map<String, Object?> json) =>
      _$Data291FromJson(json);

  final Account5 account;
  final Entry4 entry;
  final Fiscal32 fiscal;

  Map<String, Object?> toJson() => _$Data291ToJson(this);
}
