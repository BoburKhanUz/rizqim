// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entries4.dart';

part 'data228.g.dart';

@JsonSerializable()
class Data228 {
  const Data228({required this.account, required this.entries});

  factory Data228.fromJson(Map<String, Object?> json) =>
      _$Data228FromJson(json);

  final Account2 account;
  final List<Entries4> entries;

  Map<String, Object?> toJson() => _$Data228ToJson(this);
}
