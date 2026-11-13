// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Member2 _$Member2FromJson(Map<String, dynamic> json) => Member2(
  amount: json['amount'] as String,
  meals: (json['meals'] as num).toInt(),
  dailyLimit: json['daily_limit'] as String?,
  dailyAvailable: json['daily_available'] as String?,
  mealsPerDay: (json['meals_per_day'] as num?)?.toInt(),
  mealsLeft: (json['meals_left'] as num?)?.toInt(),
);

Map<String, dynamic> _$Member2ToJson(Member2 instance) => <String, dynamic>{
  'amount': instance.amount,
  'meals': instance.meals,
  'daily_limit': instance.dailyLimit,
  'daily_available': instance.dailyAvailable,
  'meals_per_day': instance.mealsPerDay,
  'meals_left': instance.mealsLeft,
};
