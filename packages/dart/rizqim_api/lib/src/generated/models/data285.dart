// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entries10.dart';

part 'data285.g.dart';

@JsonSerializable()
class Data285 {
  const Data285({required this.account, required this.entries});

  factory Data285.fromJson(Map<String, Object?> json) =>
      _$Data285FromJson(json);

  final Account2 account;
  final List<Entries10> entries;

  Map<String, Object?> toJson() => _$Data285ToJson(this);
}
